import { Router } from 'express';
import * as messagingController from '../controllers/messagingController';
import { requireAuth } from '../middlewares/auth';
import { requireActiveAccount } from '../middlewares/accountStatus';
import { validate } from '../middlewares/validate';
import { chatMessageSchema, directMessageSchema } from '../validators/messagingValidators';

export const chatRouter = Router();
chatRouter.get('/', requireAuth, requireActiveAccount, messagingController.listChatMessages);
chatRouter.post('/', requireAuth, requireActiveAccount, validate(chatMessageSchema), messagingController.createChatMessage);

export const dmRouter = Router();
dmRouter.get('/', requireAuth, requireActiveAccount, messagingController.listDirectMessages);
dmRouter.post('/', requireAuth, requireActiveAccount, validate(directMessageSchema), messagingController.createDirectMessage);
